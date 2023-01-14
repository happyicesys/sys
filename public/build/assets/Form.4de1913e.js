import{j as c,u as i,i as _,o as f,g as v,a as r,b as w,w as t,F as g,H as h,d as o,e as x,f as n}from"./app.ad0996d0.js";import{_ as V}from"./Authenticated.2894f92e.js";import{_ as l}from"./FormInput.89a1716a.js";import"./open-closed.4c312123.js";import"./use-resolve-button-type.fe156099.js";import"./RectangleStackIcon.c7c7181f.js";const b=o("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Account Settings ",-1),y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 pt-5 md:pt-2"},S=["onSubmit"],U={class:"overflow-hidden shadow sm:rounded-md"},F={class:"bg-white px-4 py-5 sm:p-6"},k={class:"grid grid-cols-12 gap-6"},N={class:"col-span-12 sm:col-span-8"},j=n(" Name "),B={class:"col-span-12 sm:col-span-6"},E=n(" Email "),T={class:"col-span-12 sm:col-span-6"},A=n(" Username "),C={class:"col-span-12 sm:col-span-6"},H=n(" Password "),L={class:"col-span-12 sm:col-span-6"},M=n(" Password Confirmation "),P=o("div",{class:"bg-gray-50 px-4 py-3 text-right sm:px-6"},[o("button",{type:"submit",class:"inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},"Save")],-1),J={__name:"Form",props:{user:Object},setup(u){const m=u;c(()=>{e.value=m.user?i({...d(),...m.user.data}):i(d())});const e=_(i(d()));function d(){return{id:"",name:"",username:"",email:"",password:"",password_confirmation:""}}function p(){e.value.clearErrors(),e.value.post("/self/"+e.value.id+"/update",{preserveState:!0,replace:!0})}return($,s)=>(f(),v(g,null,[r(w(h),{title:"Account Settings"}),r(V,null,{header:t(()=>[b]),default:t(()=>[o("div",y,[o("form",{onSubmit:x(p,["prevent"])},[o("div",U,[o("div",F,[o("div",k,[o("div",N,[r(l,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:t(()=>[j]),_:1},8,["modelValue","error"])]),o("div",B,[r(l,{modelValue:e.value.email,"onUpdate:modelValue":s[1]||(s[1]=a=>e.value.email=a),error:e.value.errors.email},{default:t(()=>[E]),_:1},8,["modelValue","error"])]),o("div",T,[r(l,{modelValue:e.value.username,"onUpdate:modelValue":s[2]||(s[2]=a=>e.value.username=a),error:e.value.errors.username},{default:t(()=>[A]),_:1},8,["modelValue","error"])]),o("div",C,[r(l,{modelValue:e.value.password,"onUpdate:modelValue":s[3]||(s[3]=a=>e.value.password=a),error:e.value.errors.password,placeholderStr:"Leave blank for same password",inputType:"password",autocomplete:"new-password"},{default:t(()=>[H]),_:1},8,["modelValue","error"])]),o("div",L,[r(l,{modelValue:e.value.password_confirmation,"onUpdate:modelValue":s[4]||(s[4]=a=>e.value.password_confirmation=a),error:e.value.errors.password_confirmation,placeholderStr:"Leave blank for same password",inputType:"password",autocomplete:"new-password-confirmation"},{default:t(()=>[M]),_:1},8,["modelValue","error"])])])]),P])],40,S)])]),_:1})],64))}};export{J as default};
